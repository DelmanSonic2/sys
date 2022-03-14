<?php

namespace Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Support\Request;
use Support\Utils;

class ExportController
{
    public static function export()
    {

        $data = Request::$request['data'];

        $col = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];


        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(24);

        foreach($data as $index => $vals){
            foreach($vals as $indexVal => $val){
                $sheet->setCellValue($col[$indexVal].($index+1), $val);
            }
        }



        $writer = new Xlsx($spreadsheet);
        $filename = time().'.xlsx';
        $writer->save('storage/xlsx/'.$filename);

        Utils::responsePlain(["file"=>'https://cwflow.apiloc.ru/storage/xlsx/'.$filename]);
    }
}
