<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ProfileQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::Profile(),
            'description' => 'Профиль',
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");


                return Auth::getProfile();
            }

        ];
    }
}