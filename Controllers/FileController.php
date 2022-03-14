<?php

namespace Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use phpthumb;
use Support\Request;
use Support\Utils;

class FileController
{
    public static function upload()
    {

        $file = $_FILES['file'];

        $filename = explode('.', $file['name']);
        $type = $filename[sizeof($filename) - 1];

        $flag = false;


        $path_to_save = ROOT . 'storage/tmp/' . md5(time() . rand(1111, 9999)) . '.' . $type;

        if (move_uploaded_file($file['tmp_name'], $path_to_save)) {


            $phpThumb = new phpthumb();
            $phpThumb->setSourceFilename($path_to_save);

            $phpThumb->setParameter("w", 300);
            $phpThumb->setParameter("h", 300);
            $phpThumb->setParameter("zc", 'C');
            $phpThumb->setParameter("f", 'jpeg');
            $phpThumb->setParameter("q", '70');


            if ($phpThumb->GenerateThumbnail()) {
                $phpThumb->RenderToFile($path_to_save);

                $url = Utils::saveToS3($path_to_save, md5($path_to_save) . '.jpg');
                unlink($path_to_save);


                Utils::response("success", [
                    'link' => $url
                ], 7);
            } else {
                Utils::response("error", [], 3);
            }
        } else {
            Utils::response("error", [], 3);
        }
    }
}