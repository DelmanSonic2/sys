<?php
use Support\Pages;
use Support\DB;
ini_set("max_execution_time", "60");
require 'PHPExcel.php';

class ExportToFile{

    protected $db;          //экземпляр системного класса
    public $data;           //данные, которые будут выгружаться в Excel файл
    protected $title;       //массив с названиями для шапки таблицы
    protected $filename;    //название файла
    //Поля, которые будут приводиться Excel к типу number
    private $number_fields = ['count','cost_price','profit','total','sum','discount','minus_points','plus_points', 'cash', 'card', 'without_discount', 'points','check_count','avg_check', 'checks', 'revenue'];
    //Поля, которые будут приводиться Excel к типу text
    private $text_fields = ['client_phone', 'phone'];
    //Поля, которые будут определять, диапазоны для формул (работает только для категорий с вложенностью)
    private $excel_range = [];
    //Поля, которые будут подсвечены серым, что означает, что они не учавствовали в расчетах
    private $hide_fields = [];

    public function __construct($db, $title, $filename){
        
        // $this->db = $db;
        $this->data = [];
        $this->title = $title;
        $this->filename = $filename;

    }

    /*
    Если передан nesting = true, то программа будет строить формулу,
    которая будет считать значения в колонке только по родителям (для категорий с вложенностью),
    иначе программа генерирует формулу с диапазоном, в котором выбирается вся колонка
    */
    public function create($nesting = false, $redirect = false){

        //Задаем стили для таблицы
        $BStyle = array(
            'borders' => array(
              'outline' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN
              )
            )
        );

        //Если нет папки, то создаем
        if(!is_dir(ROOT.'storage'))
            mkdir(ROOT.'storage', 0777);

        if(!is_dir(ROOT.'storage/xlsx'))
            mkdir(ROOT.'storage/xlsx', 0777);

        //Указываем путь, по которому будет находиться файл
        $path = 'storage/xlsx/'.$this->filename.' '.date('d-m-Y H:i:s', time()).'.xls';

        $document = new \PHPExcel();

        $sheet = $document->setActiveSheetIndex(0); // Выбираем первый лист в документе
                        
        $columnPosition = 0; // Начальная координатаx
        $startLine = 1; // Начальная координата y

        $array = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

        foreach($array as $key => $item)
            $document->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);

        $title = ["№"];

        //Формируем шапку для таблицы, используя массив, который был передан в конструкторе
        for($i = 0; $i < sizeof($this->title['columns']); $i++)
            $title[] = $this->title['columns'][$i]['title'];

        // Указатель на первый столбец
        $currentColumn = $columnPosition;

        // Формируем шапку
        foreach ($title as $column) {

            // Красим ячейку
            $sheet->getStyleByColumnAndRow($currentColumn, $startLine)
                ->getFill()
                ->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('51bfff');
            $sheet->getStyleByColumnAndRow($currentColumn, $startLine)->applyFromArray($BStyle);
            $sheet->getStyleByColumnAndRow($currentColumn, $startLine)->getFont()->setSize(14);
            $sheet->setCellValueByColumnAndRow($currentColumn, $startLine, $column);

            // Смещаемся вправо
            $currentColumn++;
        }

        //Задаем стили для всей таблицы ниже шапки
        $default_style = array(
            'font' => array(
                'name' => 'Arial',
                'color' => array('rgb' => '000000'),
                'size' => 11
            ),
            'code' => PHPExcel_Style_NumberFormat::FORMAT_TEXT,
            'alignment' => array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
                'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => '000000')
                )
            )
        );

        //Формируем строку "Итого"
        $total = array('i' => 'Итого');
        //Если значение останется false, то строка добавляться не будет
        $add_total = false;

        //Если есть вложенность, ищем главные категории в колонке, т.к. в них суммируются значения из дочерних
        if($nesting){

            for($i = 0; $i < sizeof($this->data); $i++){

                if($this->data[$i]['parent'] == null)
                    $this->excel_range[] = $i;
                else
                    $this->hide_fields[] = $i;
                
                //После итерации, удаляем поле, чтобы оно не вывелось в файл
                unset($this->data[$i]['parent']);

            }

        }

        //Проходимся по массиву с названиями колонок
        foreach($this->title['columns'] AS $key => $value){

            $range = '';
            
            //Если в классе TableHead не указана формула, для расчетов в колонке, то не выводим ничего
            if(!$value['formula'])
                $total[$value['dataIndex']] = '';
            else{
                if($nesting){
                    /*
                    Если вложенность, то обходим родителей и строим по ним диапазоны в колонке, например =SUM(C2:C2;C9:C9),
                    значит из диапазона C3:C8 значения браться не будут
                    */
                    for($i = 0; $i < sizeof($this->excel_range); $i++){

                        if(!$range){
                            $position = $array[($key + 1)].($this->excel_range[$i]+2);
                            $range = '('.$position.':'.$position;
                        }
                        else{
                            $position = $array[($key + 1)].($this->excel_range[$i]+2);
                            $range .= ','.$position.':'.$position;
                        }

                    }

                    if($range)
                        $range .= ')';

                    $total[$value['dataIndex']] = '='.$value['formula'].$range;

                }
                else//Если нет вложенности, то формируем формулу для всей колонки
                    $total[$value['dataIndex']] = '='.$value['formula'].'('.$array[$key + 1].'2:'.$array[$key + 1].(sizeof($this->data) + 1).')';
                $add_total = true;
            }
        }

        //Если была использована хоть одна формула, то добавляем строку "Итого"
        if($add_total)
            $this->data[] = $total;

        //Выделяем диапазон, для которого будут заданы стили, и куда будут добавлены значения
        $range = 'A2:'.$array[sizeof($this->title['columns'])].(sizeof($this->data) + 1);

        $document->getActiveSheet()->getStyle($range)->applyFromArray($default_style);

        //Красим в серый цвет поля, которые не учавствуют в рассчетах
        for($i = 0; $i < sizeof($this->hide_fields); $i++){

            $position = $this->hide_fields[$i] + 2; //Получаем порядковый номер строки
            $begin_row = 'A'.$position;             //Задаем координаты начала строки
            $end_row = $array[sizeof($this->title['columns'])].$position; //Задаем координаты конца строки

            $document->getActiveSheet()->getStyle($begin_row.':'.$end_row)->getFill()->applyFromArray(array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'startcolor' => array(
                     'rgb' => 'dbdbdb'
                )
            ));
        }

        $sheet->fromArray($this->data, NULL, 'A2', true);

        $objWriter = \PHPExcel_IOFactory::createWriter($document, 'Excel5');
        $objWriter->save(ROOT.$path);

        if($redirect)
            header('Location: '.SITE_URL.$path);
        else
            response('success', array('link' => SITE_URL.$path), '7');

    
    }

}