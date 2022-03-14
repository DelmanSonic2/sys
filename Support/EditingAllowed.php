<?php

namespace Support;

class EditingAllowed
{

    public static function Item($item)
    {

        if ($item['partner'] == null && Auth::$user['admin']) {
            return true;
        }

        if ($item['partner'] == Auth::$user['id']) {
            return true;
        }

        return false;
    }

    public static function TechnicalCard($item)
    {

        if ($item['enableForAll'] == true && Auth::$user['admin']) {
            return true;
        }

        if ($item['partner'] == (int) Auth::$user['id']) {
            return true;
        }

        //Глобальный доступ для Ирины
        if (Auth::$user['employee'] == 224 && Auth::$country == 'ru') {
            return true;
        }

        return false;
    }

    public static function ItemCategory()
    {
    }
}