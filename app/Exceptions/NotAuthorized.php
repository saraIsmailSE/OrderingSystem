<?php

namespace App\Exceptions;
use Exception;

class NotAuthorized extends Exception
{
    public function render()
    {
        $responseJson['statusCode']=403;
        $responseJson['message']='You have not the permission';
        return response($responseJson,403);
    }
}
