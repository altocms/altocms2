<?php
/**
 * Created by PhpStorm.
 * User: Fujitsu
 * Date: 29.01.2017
 * Time: 12:40
 */

namespace alto\engine\core;

use Zend\Diactoros\Response;

class HttpResponse extends Response
{
    public static function create()
    {
        return new static();
    }

}

// EOF