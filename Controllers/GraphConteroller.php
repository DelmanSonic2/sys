<?php

namespace Controllers;

use Controllers\GraphQL\Types;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use Support\Auth;

//Выравниватель данных
class GraphConteroller
{



    public static function init()
    {




        $schema = new Schema([
            'query' =>  Types::query(),
            'mutation' => Types::mutation()
        ]);

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $query = $input['query'];
        $variableValues = isset($input['variables']) ? $input['variables'] : null;


        try {

            //Загружаем активного юзера и его настройки если он есть, если его нет, то выкидываем ошибку
            Auth::authUser();
            //  if(!Auth::authUser()) exit;
            // throw new Error("Приватный метод");//, extensions:["code"=>"UNAUTHORIZED"]);



            $rootValue = [];


            // if (isset($_GET['debug'])) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
            $output = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues)
                ->toArray($debug);
            /* } else {

                $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
                $output = $result->toArray();
            }*/
        } catch (\Exception $e) {


            $output = [
                'errors' => [
                    [
                        'message' => $e->getMessage()
                    ]
                ]
            ];

            @file_get_contents("https://loger.apiloc.ru/loger/cw?msg=" . urlencode("CW Error GRAPH QL -  output :" . json_encode($output)));
        }
        header('Content-Type: application/json');
        echo json_encode($output);
    }
}