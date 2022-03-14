<?php
use Support\Pages;
use Support\DB;

class ProductionCostPrice{

    private $partner;
    private $point;
    private $db;
    private $iteration;

    public function __construct($db, $partner, $point = false){
        
        // $this->db = $db;
        $this->partner = $partner;
        $this->point = $point;
        $this->iteration = 1;

    }

    public function subItems($product){

        $result = [];

        if($product['untils'] == 'шт')
            $product['bulk'] = 1;

        //Получаем информацию о составе производимой продукции
        $products = DB::query('  SELECT ic.id, ic.name, ic.production, ic.bulk, prc.count, prc.gross, prc.untils, ic.round, pi.price
                                        FROM '.DB_ITEMS.' i
                                        JOIN '.DB_PRODUCTIONS_COMPOSITION.' prc ON prc.product = i.id
                                        JOIN '.DB_ITEMS.' ic ON ic.id = prc.item
                                        LEFT JOIN '.DB_POINT_ITEMS.' pi ON pi.item = prc.item AND pi.point = '.$this->point.'
                                        WHERE i.id = '.$product['id'].' AND (i.partner = '.$this->partner.' OR i.partner IS NULL)');

        while($row = DB::getRow($products)){

            //Если ингредиент в шт, то берем количество, иначе брутто
            $count = ($row['untils'] == 'шт') ? $row['count'] : $row['gross'];

            //Подстраиваем количество списываемых ингрединтов под выбранную массу полуфабриката
            $calculate_count = $count * $product['count'] / $product['bulk'];

            //Если у ингредиента стоит округление, то округляем
            if($row['round'] && $row['untils'] == 'шт'){

                //Округляем
                $rounded = round($calculate_count);

                //Если округлилось до нуля, то всё равно списываем 1 шт
                $calculate_count = ($rounded) ? $rounded : 1;
                
            }

            $row['count'] = $calculate_count;
            $row['count_price'] = $row['count'] * $row['price'];

            if($row['production']){
                $sub_items = $this->subItems($row);
                $result = array_merge($result, $sub_items);
            }
            else
                $result[] = $row;

        }

        return $result;

    }

    public function disassembly($product){

        $result = [];

        if($product['untils'] == 'шт')
            $product['bulk'] = 1;

        //Получаем информацию о составе производимой продукции
        $products = DB::query('  SELECT ic.id, ic.name, ic.production, ic.bulk, prc.count, prc.gross, prc.untils, ic.round
                                        FROM '.DB_ITEMS.' i
                                        JOIN '.DB_PRODUCTIONS_COMPOSITION.' prc ON prc.product = i.id
                                        JOIN '.DB_ITEMS.' ic ON ic.id = prc.item
                                        WHERE i.id = '.$product['id'].' AND (i.partner = '.$this->partner.' OR i.partner IS NULL)');

        while($row = DB::getRow($products)){

            //Если ингредиент в шт, то берем количество, иначе брутто
            $count = ($row['untils'] == 'шт') ? $row['count'] : $row['gross'];

            //Подстраиваем количество списываемых ингрединтов под выбранную массу полуфабриката
            $calculate_count = $count * $product['count'] / $product['bulk'];

            //Если у ингредиента стоит округление, то округляем
            if($row['round'] && $row['untils'] == 'шт'){

                //Округляем
                $rounded = round($calculate_count);

                //Если округлилось до нуля, то всё равно списываем 1 шт
                $calculate_count = ($rounded) ? $rounded : 1;
                
            }

            $row['count'] = round($calculate_count, 3);
            $row['key'] = $this->iteration;
            $this->iteration++;

            if($row['production'])
                $row['children'] = $this->disassembly($row);

            $result[] = $row;

        }

        return $result;

    }

    public function num_array_children($product){

        $result = [];

        $products = DB::query('  SELECT ic.id, ic.name, ic.production, ic.bulk, prc.count, prc.gross, prc.untils, ic.round
                                        FROM '.DB_ITEMS.' i
                                        JOIN '.DB_PRODUCTIONS_COMPOSITION.' prc ON prc.product = i.id
                                        JOIN '.DB_ITEMS.' ic ON ic.id = prc.item
                                        WHERE i.id = '.$product['id'].' AND (i.partner = '.$this->partner.' OR i.partner IS NULL)');

        while($row = DB::getRow($products)){

            if($row['production']){
                $children = $this->num_array_children($row);
                $result = array_merge($result, $children);
            }

            $result[] = $row['id'];

        }

        return $result;

    }

}