<?php

namespace Router;

use Exception;

class ViewNotFoundException extends Exception
{
    protected $message = 'View not Found';
}
