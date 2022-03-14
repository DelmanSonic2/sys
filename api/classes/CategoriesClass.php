<?php
use Support\Pages;
use Support\DB;

require_once 'ExportToFileClass.php';

class CategoriesClass extends ExportToFile{

    public $excel = [];
    public $tree = [];

    public function __construct($categories, $parent_id, $export = false){

        //Рекурсивно строим дерево
        $this->tree = $this->tree($categories, $parent_id);

        //Если не экспортируем в excel, то приводим числа к красивому формату, находим сумму значений потомков для родителя и удаляем пустые категории
        if(!$export)
            $this->tree = $this->format($this->tree);
    }

    public function tree($categories,$parent_id,$only_parent = false){
        $tree = [];
        if(is_array($categories) and isset($categories[$parent_id])){
            if($only_parent==false){
                foreach($categories[$parent_id] as $category){
                    $category['children'] =  $this->tree($categories,$category['id']);

                    if(is_array($category['children'])){
                        //Если у категории есть потомки, находим сумму значений у всех детей
                        for($i = 0; $i < count($category['children']); $i++){
                            $category['count'] += $category['children'][$i]['count'];
                            $category['cost_price'] += $category['children'][$i]['cost_price'];
                            $category['total'] += $category['children'][$i]['total'];
                            $category['profit'] += $category['children'][$i]['profit'];
                        }
                    }

                    $tree[] = $category;
                }
            }elseif(is_numeric($only_parent)){
                $category = $categories[$parent_id][$only_parent];
                $category['children'] =  $this->tree($categories,$category['id']);
                $tree[] = $category;
            }
        }
        else return false;
        return $tree;
    }

    public function format($categories){

        $tree = [];

        foreach($categories as $category){
            //Приводим числа к строке разбивая число по разрядам
            $category['count'] = ($category['count'] == 0) ? 0 : number_format($category['count'], 0, ',', ' ').' шт';
            $category['cost_price'] = ($category['cost_price'] == 0) ? 0 : number_format($category['cost_price'], 2, ',', ' ').' '.CURRENCY;
            $category['total_data'] = $category['total'];
            $category['total'] = ($category['total'] == 0) ? 0 : number_format($category['total'], 2, ',', ' ').' '.CURRENCY;
            $category['profit'] = ($category['profit'] == 0) ? 0 : number_format($category['profit'], 2, ',', ' ').' '.CURRENCY;

            if($category['children'])
                $category['children'] = $this->format($category['children']);
            //Удаляем объект, где отсутствуют все значения
            if($category['count'] || $category['cost_price'] || $category['total'] || $category['profit'])
                $tree[] = $category;

        }

        return $tree;

    }

    public function export($db, $title, $filename){

        //Поля из класса ExportToFile с областью видимости protected, их необходимо объявить, чтобы создать файл
        // $this->db = $db;
        $this->data = [];
        $this->title = $title;
        $this->filename = $filename;

        //Рекурсивно проходимся по дереву, преобразуя его в массив объектов, где под родителем будут находиться его дети
        $this->excel($this->tree);

        for($i = 0; $i < sizeof($this->excel); $i++){

            //Отправляем полученный массив в свойство класса ExportToFile
            $this->data[] = array('i' => $i+1,
                                'category' => $this->excel[$i]['category'],
                                'count' => $this->excel[$i]['count'],
                                'cost_price' => $this->excel[$i]['cost_price'],
                                'total' => $this->excel[$i]['total'],
                                'profit' => $this->excel[$i]['profit'],
                                'parent' => $this->excel[$i]['parent']);

        }

        //Вызываем функцию create класса ExportToFile
        $this->create(true);

    }

    private function excel($categories){

        foreach($categories as $category){

            //Еси есть ребенок, то вызываем функцию для поиска его детей
            if($category['children'])
                $category['children'] = $this->excel($category['children']);

            //Если хотябы одно значение присутствует, то добавляем в начало массива, если добавлять в конец, то дети окажутся над родителями
            if($category['count'] != 0 || $category['cost_price'] != 0 || $category['total'] != 0 || $category['profit'] != 0)
                array_unshift($this->excel, $category);

        }

    }

}