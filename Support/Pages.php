<?php



namespace Support;

use Support\DB;



class Pages
{

    public static $page;

    public static $field;
    public static $order;
    public static $limit;

    public static function init()
    {
        self::$page = Request::$request['page'];
        self::$field = Request::$request['field'];
        self::$order =  Request::$request['order'];
        

        $element_count = ELEMENT_COUNT;
      
        if (!self::$page || self::$page == 1) {
            self::$page = '1';
            self::$limit = '0,' . $element_count;
        } else {
            $begin = $element_count * self::$page - $element_count;
            self::$limit = $begin . ',' . $element_count;
        }
    }

    public static function GetPageInfo($query, $page)
    {

        $pages = DB::query($query);
        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else
            $total_pages = 0;

      

        $pageData = array(
            'current_page' => (int)self::$page,
            'total_pages' => $total_pages,
            'rows_count' => (int)$pages['count'],
            'page_size' => ELEMENT_COUNT
        );

        if (!$pages)
            return $pageData;

        foreach ($pages as $key => $value) {

            if ($key != 'count')
                $pageData[$key] = $value;
        }

        return $pageData;
    }
}
