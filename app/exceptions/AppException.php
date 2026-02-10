<?php

namespace App\Exceptions;

use App\Services\Log\EventLogService;
use Exception;

class AppException extends \Exception
{
    protected $di;

    public function __construct($message, $code = 0, Exception $previous = null)
    {
        global $di;
        $this->di = $di;
        parent::__construct($message, $code, $previous);
    }
}
